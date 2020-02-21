<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmPrintBill
    Inherits System.Windows.Forms.Form

    'Form overrides dispose to clean up the component list.
    <System.Diagnostics.DebuggerNonUserCode()> _
    Protected Overrides Sub Dispose(ByVal disposing As Boolean)
        Try
            If disposing AndAlso components IsNot Nothing Then
                components.Dispose()
            End If
        Finally
            MyBase.Dispose(disposing)
        End Try
    End Sub

    'Required by the Windows Form Designer
    Private components As System.ComponentModel.IContainer

    'NOTE: The following procedure is required by the Windows Form Designer
    'It can be modified using the Windows Form Designer.  
    'Do not modify it using the code editor.
    <System.Diagnostics.DebuggerStepThrough()> _
    Private Sub InitializeComponent()
        Dim DataGridViewCellStyle17 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle18 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle19 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle20 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Me.PrintDocument1 = New System.Drawing.Printing.PrintDocument()
        Me.Panel1 = New System.Windows.Forms.Panel()
        Me.lblBstyle = New System.Windows.Forms.Label()
        Me.lblTin = New System.Windows.Forms.Label()
        Me.lblAddress = New System.Windows.Forms.Label()
        Me.cargodesc = New System.Windows.Forms.ListBox()
        Me.grossbox = New System.Windows.Forms.ListBox()
        Me.ratebox = New System.Windows.Forms.ListBox()
        Me.cargobox = New System.Windows.Forms.ListBox()
        Me.servicebox = New System.Windows.Forms.ListBox()
        Me.unitbox = New System.Windows.Forms.ListBox()
        Me.qtybox = New System.Windows.Forms.ListBox()
        Me.lblParticulars = New System.Windows.Forms.Label()
        Me.lbldiscount = New System.Windows.Forms.Label()
        Me.Label2 = New System.Windows.Forms.Label()
        Me.lblduescipsi = New System.Windows.Forms.Label()
        Me.lblppa = New System.Windows.Forms.Label()
        Me.lblvat = New System.Windows.Forms.Label()
        Me.lblscipsi = New System.Windows.Forms.Label()
        Me.lbltotal = New System.Windows.Forms.Label()
        Me.lblVoyage = New System.Windows.Forms.Label()
        Me.lblVessel = New System.Windows.Forms.Label()
        Me.Label15 = New System.Windows.Forms.Label()
        Me.Label14 = New System.Windows.Forms.Label()
        Me.Label13 = New System.Windows.Forms.Label()
        Me.Label12 = New System.Windows.Forms.Label()
        Me.lblBill = New System.Windows.Forms.Label()
        Me.lblcleintname = New System.Windows.Forms.Label()
        Me.lbltime = New System.Windows.Forms.Label()
        Me.lblBillingname = New System.Windows.Forms.Label()
        Me.Label5 = New System.Windows.Forms.Label()
        Me.DataGridPrint = New System.Windows.Forms.DataGridView()
        Me.Panel1.SuspendLayout()
        CType(Me.DataGridPrint, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.SuspendLayout()
        '
        'PrintDocument1
        '
        '
        'Panel1
        '
        Me.Panel1.BackColor = System.Drawing.Color.White
        Me.Panel1.BackgroundImageLayout = System.Windows.Forms.ImageLayout.Stretch
        Me.Panel1.Controls.Add(Me.lblBstyle)
        Me.Panel1.Controls.Add(Me.lblTin)
        Me.Panel1.Controls.Add(Me.lblAddress)
        Me.Panel1.Controls.Add(Me.cargodesc)
        Me.Panel1.Controls.Add(Me.grossbox)
        Me.Panel1.Controls.Add(Me.ratebox)
        Me.Panel1.Controls.Add(Me.cargobox)
        Me.Panel1.Controls.Add(Me.servicebox)
        Me.Panel1.Controls.Add(Me.unitbox)
        Me.Panel1.Controls.Add(Me.qtybox)
        Me.Panel1.Controls.Add(Me.lblParticulars)
        Me.Panel1.Controls.Add(Me.lbldiscount)
        Me.Panel1.Controls.Add(Me.Label2)
        Me.Panel1.Controls.Add(Me.lblduescipsi)
        Me.Panel1.Controls.Add(Me.lblppa)
        Me.Panel1.Controls.Add(Me.lblvat)
        Me.Panel1.Controls.Add(Me.lblscipsi)
        Me.Panel1.Controls.Add(Me.lbltotal)
        Me.Panel1.Controls.Add(Me.lblVoyage)
        Me.Panel1.Controls.Add(Me.lblVessel)
        Me.Panel1.Controls.Add(Me.Label15)
        Me.Panel1.Controls.Add(Me.Label14)
        Me.Panel1.Controls.Add(Me.Label13)
        Me.Panel1.Controls.Add(Me.Label12)
        Me.Panel1.Controls.Add(Me.lblBill)
        Me.Panel1.Controls.Add(Me.lblcleintname)
        Me.Panel1.Controls.Add(Me.lbltime)
        Me.Panel1.Controls.Add(Me.lblBillingname)
        Me.Panel1.Controls.Add(Me.Label5)
        Me.Panel1.Controls.Add(Me.DataGridPrint)
        Me.Panel1.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Panel1.Location = New System.Drawing.Point(5, 0)
        Me.Panel1.Margin = New System.Windows.Forms.Padding(4)
        Me.Panel1.Name = "Panel1"
        Me.Panel1.Size = New System.Drawing.Size(1152, 768)
        Me.Panel1.TabIndex = 1
        '
        'lblBstyle
        '
        Me.lblBstyle.AutoSize = True
        Me.lblBstyle.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblBstyle.Location = New System.Drawing.Point(128, 224)
        Me.lblBstyle.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblBstyle.Name = "lblBstyle"
        Me.lblBstyle.Size = New System.Drawing.Size(46, 17)
        Me.lblBstyle.TabIndex = 66
        Me.lblBstyle.Text = "Bstyle"
        '
        'lblTin
        '
        Me.lblTin.AutoSize = True
        Me.lblTin.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblTin.Location = New System.Drawing.Point(128, 252)
        Me.lblTin.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblTin.Name = "lblTin"
        Me.lblTin.Size = New System.Drawing.Size(28, 17)
        Me.lblTin.TabIndex = 65
        Me.lblTin.Text = "Tin"
        '
        'lblAddress
        '
        Me.lblAddress.AutoSize = True
        Me.lblAddress.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblAddress.Location = New System.Drawing.Point(128, 196)
        Me.lblAddress.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblAddress.Name = "lblAddress"
        Me.lblAddress.Size = New System.Drawing.Size(60, 17)
        Me.lblAddress.TabIndex = 64
        Me.lblAddress.Text = "Address"
        '
        'cargodesc
        '
        Me.cargodesc.FormattingEnabled = True
        Me.cargodesc.ItemHeight = 17
        Me.cargodesc.Location = New System.Drawing.Point(980, 17)
        Me.cargodesc.Margin = New System.Windows.Forms.Padding(4)
        Me.cargodesc.Name = "cargodesc"
        Me.cargodesc.Size = New System.Drawing.Size(159, 106)
        Me.cargodesc.TabIndex = 57
        '
        'grossbox
        '
        Me.grossbox.FormattingEnabled = True
        Me.grossbox.ItemHeight = 17
        Me.grossbox.Location = New System.Drawing.Point(865, 15)
        Me.grossbox.Margin = New System.Windows.Forms.Padding(4)
        Me.grossbox.Name = "grossbox"
        Me.grossbox.Size = New System.Drawing.Size(159, 106)
        Me.grossbox.TabIndex = 56
        '
        'ratebox
        '
        Me.ratebox.FormattingEnabled = True
        Me.ratebox.ItemHeight = 17
        Me.ratebox.Location = New System.Drawing.Point(697, 17)
        Me.ratebox.Margin = New System.Windows.Forms.Padding(4)
        Me.ratebox.Name = "ratebox"
        Me.ratebox.Size = New System.Drawing.Size(159, 106)
        Me.ratebox.TabIndex = 55
        '
        'cargobox
        '
        Me.cargobox.FormattingEnabled = True
        Me.cargobox.ItemHeight = 17
        Me.cargobox.Location = New System.Drawing.Point(529, 17)
        Me.cargobox.Margin = New System.Windows.Forms.Padding(4)
        Me.cargobox.Name = "cargobox"
        Me.cargobox.Size = New System.Drawing.Size(159, 106)
        Me.cargobox.TabIndex = 54
        '
        'servicebox
        '
        Me.servicebox.FormattingEnabled = True
        Me.servicebox.ItemHeight = 17
        Me.servicebox.Location = New System.Drawing.Point(360, 17)
        Me.servicebox.Margin = New System.Windows.Forms.Padding(4)
        Me.servicebox.Name = "servicebox"
        Me.servicebox.Size = New System.Drawing.Size(159, 106)
        Me.servicebox.TabIndex = 53
        '
        'unitbox
        '
        Me.unitbox.FormattingEnabled = True
        Me.unitbox.ItemHeight = 17
        Me.unitbox.Location = New System.Drawing.Point(192, 17)
        Me.unitbox.Margin = New System.Windows.Forms.Padding(4)
        Me.unitbox.Name = "unitbox"
        Me.unitbox.Size = New System.Drawing.Size(159, 106)
        Me.unitbox.TabIndex = 52
        '
        'qtybox
        '
        Me.qtybox.FormattingEnabled = True
        Me.qtybox.ItemHeight = 17
        Me.qtybox.Location = New System.Drawing.Point(24, 17)
        Me.qtybox.Margin = New System.Windows.Forms.Padding(4)
        Me.qtybox.Name = "qtybox"
        Me.qtybox.Size = New System.Drawing.Size(159, 106)
        Me.qtybox.TabIndex = 51
        '
        'lblParticulars
        '
        Me.lblParticulars.AutoSize = True
        Me.lblParticulars.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblParticulars.Location = New System.Drawing.Point(665, 295)
        Me.lblParticulars.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblParticulars.Name = "lblParticulars"
        Me.lblParticulars.Size = New System.Drawing.Size(0, 17)
        Me.lblParticulars.TabIndex = 50
        '
        'lbldiscount
        '
        Me.lbldiscount.AutoSize = True
        Me.lbldiscount.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lbldiscount.Location = New System.Drawing.Point(531, 559)
        Me.lbldiscount.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lbldiscount.Name = "lbldiscount"
        Me.lbldiscount.Size = New System.Drawing.Size(67, 17)
        Me.lbldiscount.TabIndex = 49
        Me.lbldiscount.Text = "VESSEL"
        '
        'Label2
        '
        Me.Label2.AutoSize = True
        Me.Label2.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label2.Location = New System.Drawing.Point(531, 544)
        Me.Label2.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label2.Name = "Label2"
        Me.Label2.Size = New System.Drawing.Size(148, 17)
        Me.Label2.TabIndex = 48
        Me.Label2.Text = "TOTAL DISCOUNT:" & Global.Microsoft.VisualBasic.ChrW(13) & Global.Microsoft.VisualBasic.ChrW(10)
        '
        'lblduescipsi
        '
        Me.lblduescipsi.AutoSize = True
        Me.lblduescipsi.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblduescipsi.Location = New System.Drawing.Point(909, 574)
        Me.lblduescipsi.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblduescipsi.Name = "lblduescipsi"
        Me.lblduescipsi.Size = New System.Drawing.Size(67, 17)
        Me.lblduescipsi.TabIndex = 47
        Me.lblduescipsi.Text = "VESSEL"
        '
        'lblppa
        '
        Me.lblppa.AutoSize = True
        Me.lblppa.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblppa.Location = New System.Drawing.Point(331, 588)
        Me.lblppa.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblppa.Name = "lblppa"
        Me.lblppa.Size = New System.Drawing.Size(67, 17)
        Me.lblppa.TabIndex = 46
        Me.lblppa.Text = "VESSEL"
        '
        'lblvat
        '
        Me.lblvat.AutoSize = True
        Me.lblvat.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblvat.Location = New System.Drawing.Point(745, 559)
        Me.lblvat.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblvat.Name = "lblvat"
        Me.lblvat.Size = New System.Drawing.Size(67, 17)
        Me.lblvat.TabIndex = 45
        Me.lblvat.Text = "VESSEL"
        '
        'lblscipsi
        '
        Me.lblscipsi.AutoSize = True
        Me.lblscipsi.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblscipsi.Location = New System.Drawing.Point(531, 588)
        Me.lblscipsi.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblscipsi.Name = "lblscipsi"
        Me.lblscipsi.Size = New System.Drawing.Size(67, 17)
        Me.lblscipsi.TabIndex = 44
        Me.lblscipsi.Text = "VESSEL"
        '
        'lbltotal
        '
        Me.lbltotal.AutoSize = True
        Me.lbltotal.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lbltotal.Location = New System.Drawing.Point(331, 559)
        Me.lbltotal.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lbltotal.Name = "lbltotal"
        Me.lbltotal.Size = New System.Drawing.Size(67, 17)
        Me.lbltotal.TabIndex = 43
        Me.lbltotal.Text = "VESSEL"
        '
        'lblVoyage
        '
        Me.lblVoyage.AutoSize = True
        Me.lblVoyage.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblVoyage.Location = New System.Drawing.Point(397, 295)
        Me.lblVoyage.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblVoyage.Name = "lblVoyage"
        Me.lblVoyage.Size = New System.Drawing.Size(72, 17)
        Me.lblVoyage.TabIndex = 42
        Me.lblVoyage.Text = "VOYAGE"
        '
        'lblVessel
        '
        Me.lblVessel.AutoSize = True
        Me.lblVessel.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblVessel.Location = New System.Drawing.Point(167, 295)
        Me.lblVessel.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblVessel.Name = "lblVessel"
        Me.lblVessel.Size = New System.Drawing.Size(67, 17)
        Me.lblVessel.TabIndex = 41
        Me.lblVessel.Text = "VESSEL"
        '
        'Label15
        '
        Me.Label15.AutoSize = True
        Me.Label15.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label15.Location = New System.Drawing.Point(909, 544)
        Me.Label15.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label15.Name = "Label15"
        Me.Label15.Size = New System.Drawing.Size(130, 34)
        Me.Label15.TabIndex = 13
        Me.Label15.Text = "TOTAL AMOUNT" & Global.Microsoft.VisualBasic.ChrW(13) & Global.Microsoft.VisualBasic.ChrW(10) & "DUE TO SCIPSI:" & Global.Microsoft.VisualBasic.ChrW(13) & Global.Microsoft.VisualBasic.ChrW(10)
        '
        'Label14
        '
        Me.Label14.AutoSize = True
        Me.Label14.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label14.Location = New System.Drawing.Point(745, 544)
        Me.Label14.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label14.Name = "Label14"
        Me.Label14.Size = New System.Drawing.Size(79, 17)
        Me.Label14.TabIndex = 12
        Me.Label14.Text = "12% VAT:"
        '
        'Label13
        '
        Me.Label13.AutoSize = True
        Me.Label13.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label13.Location = New System.Drawing.Point(531, 574)
        Me.Label13.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label13.Name = "Label13"
        Me.Label13.Size = New System.Drawing.Size(118, 17)
        Me.Label13.TabIndex = 11
        Me.Label13.Text = "SCIPSI SHARE:" & Global.Microsoft.VisualBasic.ChrW(13) & Global.Microsoft.VisualBasic.ChrW(10)
        '
        'Label12
        '
        Me.Label12.AutoSize = True
        Me.Label12.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label12.Location = New System.Drawing.Point(331, 574)
        Me.Label12.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label12.Name = "Label12"
        Me.Label12.Size = New System.Drawing.Size(100, 17)
        Me.Label12.TabIndex = 10
        Me.Label12.Text = "PPA SHARE:"
        '
        'lblBill
        '
        Me.lblBill.AutoSize = True
        Me.lblBill.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblBill.Location = New System.Drawing.Point(896, 118)
        Me.lblBill.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblBill.Name = "lblBill"
        Me.lblBill.Size = New System.Drawing.Size(110, 17)
        Me.lblBill.TabIndex = 8
        Me.lblBill.Text = "BILL NUMBER"
        '
        'lblcleintname
        '
        Me.lblcleintname.AutoSize = True
        Me.lblcleintname.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblcleintname.Location = New System.Drawing.Point(128, 167)
        Me.lblcleintname.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblcleintname.Name = "lblcleintname"
        Me.lblcleintname.Size = New System.Drawing.Size(110, 17)
        Me.lblcleintname.TabIndex = 7
        Me.lblcleintname.Text = "CLIENT NAME"
        '
        'lbltime
        '
        Me.lbltime.AutoSize = True
        Me.lbltime.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lbltime.Location = New System.Drawing.Point(896, 236)
        Me.lbltime.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lbltime.Name = "lbltime"
        Me.lbltime.Size = New System.Drawing.Size(49, 17)
        Me.lbltime.TabIndex = 6
        Me.lbltime.Text = "DATE"
        '
        'lblBillingname
        '
        Me.lblBillingname.AutoSize = True
        Me.lblBillingname.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblBillingname.Location = New System.Drawing.Point(665, 650)
        Me.lblBillingname.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblBillingname.Name = "lblBillingname"
        Me.lblBillingname.Size = New System.Drawing.Size(57, 17)
        Me.lblBillingname.TabIndex = 2
        Me.lblBillingname.Text = "Label4"
        '
        'Label5
        '
        Me.Label5.AutoSize = True
        Me.Label5.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label5.Location = New System.Drawing.Point(331, 544)
        Me.Label5.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label5.Name = "Label5"
        Me.Label5.Size = New System.Drawing.Size(143, 17)
        Me.Label5.TabIndex = 1
        Me.Label5.Text = "TOTAL CHARGES:"
        '
        'DataGridPrint
        '
        Me.DataGridPrint.AllowUserToAddRows = False
        Me.DataGridPrint.AllowUserToDeleteRows = False
        DataGridViewCellStyle17.BackColor = System.Drawing.Color.White
        Me.DataGridPrint.AlternatingRowsDefaultCellStyle = DataGridViewCellStyle17
        Me.DataGridPrint.BackgroundColor = System.Drawing.Color.White
        Me.DataGridPrint.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.DataGridPrint.ColumnHeadersBorderStyle = System.Windows.Forms.DataGridViewHeaderBorderStyle.[Single]
        DataGridViewCellStyle18.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleCenter
        DataGridViewCellStyle18.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle18.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle18.ForeColor = System.Drawing.SystemColors.ControlText
        DataGridViewCellStyle18.SelectionBackColor = System.Drawing.Color.LightSteelBlue
        DataGridViewCellStyle18.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        DataGridViewCellStyle18.WrapMode = System.Windows.Forms.DataGridViewTriState.[True]
        Me.DataGridPrint.ColumnHeadersDefaultCellStyle = DataGridViewCellStyle18
        Me.DataGridPrint.ColumnHeadersHeight = 24
        Me.DataGridPrint.Cursor = System.Windows.Forms.Cursors.Hand
        Me.DataGridPrint.EnableHeadersVisualStyles = False
        Me.DataGridPrint.GridColor = System.Drawing.Color.White
        Me.DataGridPrint.Location = New System.Drawing.Point(75, 341)
        Me.DataGridPrint.Margin = New System.Windows.Forms.Padding(4)
        Me.DataGridPrint.MultiSelect = False
        Me.DataGridPrint.Name = "DataGridPrint"
        Me.DataGridPrint.ReadOnly = True
        Me.DataGridPrint.RowHeadersBorderStyle = System.Windows.Forms.DataGridViewHeaderBorderStyle.[Single]
        DataGridViewCellStyle19.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleLeft
        DataGridViewCellStyle19.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle19.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle19.ForeColor = System.Drawing.Color.White
        DataGridViewCellStyle19.SelectionBackColor = System.Drawing.Color.White
        DataGridViewCellStyle19.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        DataGridViewCellStyle19.WrapMode = System.Windows.Forms.DataGridViewTriState.[True]
        Me.DataGridPrint.RowHeadersDefaultCellStyle = DataGridViewCellStyle19
        Me.DataGridPrint.RowHeadersVisible = False
        Me.DataGridPrint.RowHeadersWidth = 25
        Me.DataGridPrint.RowHeadersWidthSizeMode = System.Windows.Forms.DataGridViewRowHeadersWidthSizeMode.DisableResizing
        DataGridViewCellStyle20.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle20.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle20.ForeColor = System.Drawing.Color.Black
        DataGridViewCellStyle20.SelectionBackColor = System.Drawing.Color.White
        DataGridViewCellStyle20.SelectionForeColor = System.Drawing.Color.Black
        Me.DataGridPrint.RowsDefaultCellStyle = DataGridViewCellStyle20
        Me.DataGridPrint.RowTemplate.Height = 18
        Me.DataGridPrint.RowTemplate.Resizable = System.Windows.Forms.DataGridViewTriState.[False]
        Me.DataGridPrint.SelectionMode = System.Windows.Forms.DataGridViewSelectionMode.CellSelect
        Me.DataGridPrint.Size = New System.Drawing.Size(1001, 199)
        Me.DataGridPrint.TabIndex = 40
        '
        'frmPrintBill
        '
        Me.AutoScaleDimensions = New System.Drawing.SizeF(8.0!, 16.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.ClientSize = New System.Drawing.Size(1161, 772)
        Me.Controls.Add(Me.Panel1)
        Me.Margin = New System.Windows.Forms.Padding(4)
        Me.Name = "frmPrintBill"
        Me.Text = "frmPrintBill"
        Me.Panel1.ResumeLayout(False)
        Me.Panel1.PerformLayout()
        CType(Me.DataGridPrint, System.ComponentModel.ISupportInitialize).EndInit()
        Me.ResumeLayout(False)

    End Sub
    Friend WithEvents PrintDocument1 As System.Drawing.Printing.PrintDocument
    Friend WithEvents Panel1 As System.Windows.Forms.Panel
    Friend WithEvents lblBillingname As System.Windows.Forms.Label
    Friend WithEvents lblBill As System.Windows.Forms.Label
    Friend WithEvents lblcleintname As System.Windows.Forms.Label
    Friend WithEvents lbltime As System.Windows.Forms.Label
    Friend WithEvents Label5 As System.Windows.Forms.Label
    Friend WithEvents Label15 As System.Windows.Forms.Label
    Friend WithEvents Label14 As System.Windows.Forms.Label
    Friend WithEvents Label13 As System.Windows.Forms.Label
    Friend WithEvents Label12 As System.Windows.Forms.Label
    Friend WithEvents DataGridPrint As System.Windows.Forms.DataGridView
    Friend WithEvents lblVoyage As System.Windows.Forms.Label
    Friend WithEvents lblVessel As System.Windows.Forms.Label
    Friend WithEvents lblduescipsi As System.Windows.Forms.Label
    Friend WithEvents lblppa As System.Windows.Forms.Label
    Friend WithEvents lblvat As System.Windows.Forms.Label
    Friend WithEvents lblscipsi As System.Windows.Forms.Label
    Friend WithEvents lbltotal As System.Windows.Forms.Label
    Friend WithEvents lbldiscount As System.Windows.Forms.Label
    Friend WithEvents Label2 As System.Windows.Forms.Label
    Friend WithEvents lblParticulars As System.Windows.Forms.Label
    Friend WithEvents qtybox As System.Windows.Forms.ListBox
    Friend WithEvents grossbox As System.Windows.Forms.ListBox
    Friend WithEvents ratebox As System.Windows.Forms.ListBox
    Friend WithEvents cargobox As System.Windows.Forms.ListBox
    Friend WithEvents servicebox As System.Windows.Forms.ListBox
    Friend WithEvents unitbox As System.Windows.Forms.ListBox
    Friend WithEvents cargodesc As System.Windows.Forms.ListBox
    Friend WithEvents lblBstyle As Label
    Friend WithEvents lblTin As Label
    Friend WithEvents lblAddress As Label
End Class
