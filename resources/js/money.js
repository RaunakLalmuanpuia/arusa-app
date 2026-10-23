export const formatPaise = (paise) =>
    `₹${(paise / 100).toLocaleString('en-IN', {
        minimumFractionDigits: paise % 100 ? 2 : 0,
        maximumFractionDigits: 2,
    })}`;

export const toPaise = (rupees) => (rupees === '' || rupees === null ? null : Math.round(Number(rupees) * 100));
